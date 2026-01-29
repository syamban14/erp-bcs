<?php

namespace App\Services;

class GeofencingService
{
    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude point 1
     * @param float $lng1 Longitude point 1
     * @param float $lat2 Latitude point 2
     * @param float $lng2 Longitude point 2
     * @return float Distance in meters
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371000; // Earth radius in meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        $distance = $earthRadius * $c;
        
        return $distance;
    }
    
    /**
     * Check if user location is within office radius
     * 
     * @param float $userLat User latitude
     * @param float $userLng User longitude
     * @param float $officeLat Office latitude
     * @param float $officeLng Office longitude
     * @param int $radius Radius in meters
     * @return bool
     */
    public function isWithinRadius($userLat, $userLng, $officeLat, $officeLng, $radius): bool
    {
        $distance = $this->calculateDistance($userLat, $userLng, $officeLat, $officeLng);
        return $distance <= $radius;
    }
    
    /**
     * Validate location and return detailed result
     * 
     * @param float $userLat User latitude
     * @param float $userLng User longitude
     * @param float $officeLat Office latitude
     * @param float $officeLng Office longitude
     * @param int $radius Radius in meters
     * @return array
     */
    public function validate($userLat, $userLng, $officeLat, $officeLng, $radius): array
    {
        $distance = $this->calculateDistance($userLat, $userLng, $officeLat, $officeLng);
        $isValid = $distance <= $radius;
        
        return [
            'is_valid' => $isValid,
            'distance' => round($distance, 2),
            'radius' => $radius,
            'message' => $isValid 
                ? 'Lokasi valid' 
                : "Gagal absen. Lokasi Anda berada di luar jangkauan kantor. Jarak: " . round($distance) . " meter dari kantor."
        ];
    }
}
