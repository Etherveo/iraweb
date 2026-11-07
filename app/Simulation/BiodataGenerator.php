<?php

namespace App\Simulation;

// M4 simulator (SIM-06, blueprint §9.3). The single contract behind
// which the owner's biodata script sits: given a count, an offset and
// an optional seed, return that many complete biodata rows. The PHP
// side never knows whether the rows came from Python, a test double,
// or a future implementation.
interface BiodataGenerator
{
    /**
     * @return list<array{
     *   name: string, email: string, username: ?string, phone: ?string,
     *   gender: ?string, birthdate: ?string, // Y-m-d
     *   address: array{recipient_name: string, recipient_phone: ?string,
     *     province: string, city: string, district: string, village: string,
     *     postal_code: string, street_address: string, address_type: string},
     * }>
     * Email wajib berdomain config('simulation.email_domain') dan unik
     * di dalam hasil. Daftar diurutkan stabil (indeks 0..n-1).
     */
    public function generate(int $count, int $offset, ?int $seed): array;
}
