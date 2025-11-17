<?php

class SlotBookAnalytics
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    // Get peak booking hours
    public function getPeakHours()
    {
        $query = "
            SELECT HOUR(start_time) as hour, COUNT(*) as booking_count 
            FROM reservations 
            WHERE status = 'approved' 
            GROUP BY HOUR(start_time) 
            ORDER BY booking_count DESC 
            LIMIT 6
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        $peakHours = [];
        $totalBookings = 0;
        $hourData = [];

        while ($row = $result->fetch_assoc()) {
            $peakHours[] = $row;
            $totalBookings += $row['booking_count'];
            $hourData[$row['hour']] = $row['booking_count'];
        }

        return [
            'peak_hours' => $peakHours,
            'total_bookings' => $totalBookings,
            'hour_data' => $hourData
        ];
    }

    // Get most requested facilities
    public function getPopularFacilities($limit = 5)
    {
        $query = "
            SELECT f.name, f.id, COUNT(r.id) as request_count 
            FROM facilities f 
            LEFT JOIN reservations r ON f.id = r.facility_id 
            WHERE r.status = 'approved' 
            GROUP BY f.id 
            ORDER BY request_count DESC 
            LIMIT ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $facilities = [];
        while ($row = $result->fetch_assoc()) {
            $facilities[] = $row;
        }

        return $facilities;
    }

    // Get slot utilization rate
    public function getUtilizationRate($days = 7)
    {
        $query = "
            SELECT 
                COUNT(*) as total_slots,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as booked_slots
            FROM reservations 
            WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if ($data['total_slots'] > 0) {
            $utilization = round(($data['booked_slots'] / $data['total_slots']) * 100, 1);
        } else {
            $utilization = 0;
        }

        return [
            'utilization_rate' => $utilization,
            'booked_slots' => $data['booked_slots'],
            'total_slots' => $data['total_slots'],
            'period_days' => $days
        ];
    }

    // Get booking trends
    public function getBookingTrends($days = 30)
    {
        $query = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as daily_bookings,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as confirmed_bookings
            FROM reservations 
            WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();

        $trends = [];
        while ($row = $result->fetch_assoc()) {
            $trends[] = $row;
        }

        return array_reverse($trends); // Return in chronological order
    }

    // Generate smart insight
    public function generateInsights()
    {
        $peakData = $this->getPeakHours();
        $facilities = $this->getPopularFacilities(3);
        $utilization = $this->getUtilizationRate();
        $trends = $this->getBookingTrends(7);

        $insights = [];

        // Peak hours insight
        if (!empty($peakData['peak_hours'])) {
            $topHour = $peakData['peak_hours'][0];
            $peakHours = array_slice($peakData['peak_hours'], 0, 3);
            $hourRanges = [];

            foreach ($peakHours as $hour) {
                $hourRanges[] = $hour['hour'] . ':00';
            }

            $peakPeriod = implode(', ', $hourRanges);
            $insights[] = [
                'type' => 'peak_hours',
                'title' => 'Peak Booking Hours',
                'message' => "Peak booking hours are " . $peakPeriod . " with " . $topHour['booking_count'] . " bookings",
                'priority' => 'high'
            ];
        }

        // Popular facilities insight
        if (!empty($facilities)) {
            $topFacility = $facilities[0];
            $insights[] = [
                'type' => 'popular_facility',
                'title' => 'Most Requested Facility',
                'message' => "Most requested facility: " . $topFacility['name'] . " (" . $topFacility['request_count'] . " bookings)",
                'priority' => 'medium'
            ];
        }

        // Utilization insight
        $insights[] = [
            'type' => 'utilization',
            'title' => 'Slot Utilization',
            'message' => "Slot utilization: " . $utilization['utilization_rate'] . "% this week (" . $utilization['booked_slots'] . "/" . $utilization['total_slots'] . " slots booked)",
            'priority' => $utilization['utilization_rate'] > 80 ? 'high' : 'medium'
        ];

        // Trend insight
        if (count($trends) >= 2) {
            $recent = end($trends);
            $previous = prev($trends);

            if ($previous['confirmed_bookings'] > 0) {
                $trend = (($recent['confirmed_bookings'] - $previous['confirmed_bookings']) / $previous['confirmed_bookings']) * 100;

                if (abs($trend) > 10) {
                    $trendText = $trend > 0 ? "increased" : "decreased";
                    $insights[] = [
                        'type' => 'trend',
                        'title' => 'Booking Trend',
                        'message' => "Bookings have " . $trendText . " by " . abs(round($trend)) . "% compared to yesterday",
                        'priority' => 'medium'
                    ];
                }
            }
        }

        return $insights;
    }


    public function predictBusyPeriods()
    {
        $query = "
        SELECT 
            f.name as facility_name,
            f.building,
            DAYNAME(r.date) as day_name,
            DAYOFWEEK(r.date) as day_num,
            HOUR(r.start_time) as hour,
            COUNT(*) as recent_bookings
        FROM reservations r
        JOIN facilities f ON r.facility_id = f.id
        WHERE r.status = 'approved' 
        AND r.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY r.facility_id, DAYOFWEEK(r.date), HOUR(r.start_time)
        HAVING recent_bookings >= 1
        ORDER BY recent_bookings DESC, day_num, hour
        LIMIT 15
    ";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        $predictions = [];
        $nextWeekStart = date('Y-m-d', strtotime('next monday'));

        while ($row = $result->fetch_assoc()) {
            $predictedDate = date('Y-m-d', strtotime($nextWeekStart . " + " . ($row['day_num'] - 1) . " days"));

            // Make predictions appear immediately
            $busyLevel = $row['recent_bookings'] > 2 ? 'high' : 'medium';

            $predictions[] = [
                'date' => $predictedDate,
                'day' => $row['day_name'],
                'hour' => $row['hour'],
                'busy_level' => $busyLevel,
                'predicted_demand' => $row['recent_bookings'],
                'time_slot' => $row['hour'] . ':00 - ' . ($row['hour'] + 1) . ':00',
                'facility_name' => $row['facility_name'],
                'building' => $row['building'],
                'recent_bookings' => $row['recent_bookings']
            ];
        }

        return $predictions;
    }

    // Suggest optimal maintenance schedules
    public function suggestMaintenanceSchedules()
    {
        $query = "
            SELECT 
                f.id,
                f.name,
                f.building,
                COUNT(r.id) as total_bookings,
                MAX(r.date) as last_booking_date,
                DATEDIFF(CURDATE(), MAX(r.date)) as days_since_last_booking
            FROM facilities f
            LEFT JOIN reservations r ON f.id = r.facility_id AND r.status = 'approved'
            WHERE f.status = 'available'
            GROUP BY f.id
            HAVING total_bookings > 0
            ORDER BY total_bookings DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        $suggestions = [];

        while ($row = $result->fetch_assoc()) {
            $maintenancePriority = 'medium';

            // High priority if heavily used or hasn't been used in a while
            if ($row['total_bookings'] > 20) {
                $maintenancePriority = 'high';
                $reason = "High usage facility";
            } elseif ($row['days_since_last_booking'] > 14) {
                $maintenancePriority = 'high';
                $reason = "Ideal time for maintenance (low usage period)";
            } elseif ($row['total_bookings'] > 10) {
                $maintenancePriority = 'medium';
                $reason = "Moderate usage - schedule maintenance soon";
            } else {
                $maintenancePriority = 'low';
                $reason = "Low usage facility";
            }

            $suggestions[] = [
                'facility_id' => $row['id'],
                'facility_name' => $row['name'],
                'building' => $row['building'],
                'total_bookings' => $row['total_bookings'],
                'last_booking' => $row['last_booking_date'],
                'priority' => $maintenancePriority,
                'reason' => $reason,
                'suggested_date' => date('Y-m-d', strtotime('+3 days')) // Suggest maintenance in 3 days
            ];
        }

        return $suggestions;
    }

    // Recommend facility upgrades
    public function recommendFacilityUpgrades()
    {
        $query = "
            SELECT 
                f.id,
                f.name,
                f.building,
                f.capacity,
                COUNT(r.id) as total_requests,
                SUM(CASE WHEN r.status = 'denied' THEN 1 ELSE 0 END) as denied_requests
            FROM facilities f
            LEFT JOIN reservations r ON f.id = r.facility_id
            WHERE r.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY f.id
            HAVING total_requests > 5
            ORDER BY denied_requests DESC, total_requests DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        $recommendations = [];

        while ($row = $result->fetch_assoc()) {
            $denialRate = $row['total_requests'] > 0 ? ($row['denied_requests'] / $row['total_requests']) * 100 : 0;

            if ($denialRate > 30) {
                $recommendation = "High demand - consider adding similar facilities";
                $priority = "high";
            } elseif ($row['total_requests'] > 20) {
                $recommendation = "Popular facility - consider expanding capacity";
                $priority = "medium";
            } elseif ($denialRate > 15) {
                $recommendation = "Moderate denial rate - monitor capacity";
                $priority = "medium";
            } else {
                continue; // Skip facilities that don't need upgrades
            }

            $recommendations[] = [
                'facility_id' => $row['id'],
                'facility_name' => $row['name'],
                'building' => $row['building'],
                'current_capacity' => $row['capacity'],
                'denial_rate' => round($denialRate, 1),
                'total_requests' => $row['total_requests'],
                'recommendation' => $recommendation,
                'priority' => $priority
            ];
        }

        return $recommendations;
    }

    // Generate intelligent insights with recommendations
    public function generateIntelligentInsights()
    {
        $basicInsights = $this->generateInsights();
        $predictions = $this->predictBusyPeriods();
        $maintenanceSuggestions = $this->suggestMaintenanceSchedules();
        $upgradeRecommendations = $this->recommendFacilityUpgrades();

        $intelligentInsights = [];

        // Prediction insights
        if (!empty($predictions)) {
            $highDemandPeriods = array_filter($predictions, function ($p) {
                return $p['busy_level'] == 'high';
            });
            if (count($highDemandPeriods) > 0) {
                $intelligentInsights[] = [
                    'type' => 'prediction',
                    'title' => 'High Demand Prediction',
                    'message' => count($highDemandPeriods) . " high-demand periods predicted next week",
                    'priority' => 'high',
                    'data' => array_slice($highDemandPeriods, 0, 3)
                ];
            }
        }

        // Maintenance insights
        if (!empty($maintenanceSuggestions)) {
            $highPriorityMaintenance = array_filter($maintenanceSuggestions, function ($m) {
                return $m['priority'] == 'high';
            });
            if (count($highPriorityMaintenance) > 0) {
                $intelligentInsights[] = [
                    'type' => 'maintenance',
                    'title' => 'Maintenance Recommendations',
                    'message' => count($highPriorityMaintenance) . " facilities need priority maintenance",
                    'priority' => 'medium',
                    'data' => array_slice($highPriorityMaintenance, 0, 3)
                ];
            }
        }

        // Upgrade recommendations
        if (!empty($upgradeRecommendations)) {
            $intelligentInsights[] = [
                'type' => 'upgrade',
                'title' => 'Facility Upgrade Suggestions',
                'message' => count($upgradeRecommendations) . " facilities may need upgrades",
                'priority' => 'medium',
                'data' => $upgradeRecommendations
            ];
        }

        // Merge with basic insights
        return array_merge($basicInsights, $intelligentInsights);
    }
}
?>