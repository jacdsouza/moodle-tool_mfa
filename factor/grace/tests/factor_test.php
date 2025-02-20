<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace factor_grace\tests;

/**
 * Tests for grace factor.
 *
 * @package     factor_grace
 * @author      Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factor_test extends \advanced_testcase {

    public function test_affecting_factors() {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $grace = \tool_mfa\plugininfo\factor::get_factor('grace');
        $affecting = $grace->get_affecting_factors();
        $this->assertEquals(0, count($affecting));

        set_config('enabled', 1, 'factor_totp');
        $totpfactor = \tool_mfa\plugininfo\factor::get_factor('totp');
        $totpdata = [
            'secret' => 'fakekey',
            'devicename' => 'fakedevice',
        ];
        $totpfactor->setup_user_factor((object) $totpdata);

        // Confirm that MFA is the only affecting factor.
        $affecting = $grace->get_affecting_factors();
        $this->assertEquals(1, count($affecting));
        $totp = reset($affecting);
        $this->assertTrue($totp instanceof \factor_totp\factor);

        // Now put it in the ignorelist.
        set_config('ignorelist', 'totp', 'factor_grace');
        // Confirm that MFA is the only affecting factor.
        $affecting = $grace->get_affecting_factors();
        $this->assertEquals(0, count($affecting));
    }

    /**
     * Test factors leading to a redirect.
     */
    public function test_redirect_factors() {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $grace = \tool_mfa\plugininfo\factor::get_factor('grace');

        set_config('enabled', 1, 'factor_totp');
        set_config('enabled', 1, 'factor_grace');
        set_config('forcesetup', 1, 'factor_grace');
        set_config('graceperiod', -1, 'factor_grace'); // Grace period expired.

        // Set up exemption factor for a person.
        set_config('duration', 100, 'factor_exemption');
        \factor_exemption\factor::add_exemption($user);

        $redirected = false;
        try {
            $grace->get_state(true);
        } catch (\Throwable $e) {
            $expected = get_string('redirecterrordetected', 'error');
            if ($expected === $e->getMessage()) {
                $redirected = true;
            }
        }

        $this->assertTrue($redirected, 'No redirect detected, but was expected.');
    }

    /**
     * Test factors leading to a redirect, but avoiding it
     */
    public function test_gracemode_expires_noredirect() {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $grace = \tool_mfa\plugininfo\factor::get_factor('grace');
        $totp = \tool_mfa\plugininfo\factor::get_factor('totp');
        $exemption = \tool_mfa\plugininfo\factor::get_factor('exemption');

        set_config('enabled', 1, 'factor_totp');
        set_config('enabled', 1, 'factor_exemption');
        set_config('enabled', 1, 'factor_grace');
        set_config('forcesetup', 1, 'factor_grace');
        set_config('graceperiod', -1, 'factor_grace'); // Grace period expired.

        // Set up exemption factor for a person.
        set_config('duration', 100, 'factor_exemption');
        \factor_exemption\factor::add_exemption($user);

        // Set exemption as a factor that should prevent redirects.
        set_config('noredirectlist', 'exemption', 'factor_grace');

        $redirected = false;
        try {
            $grace->get_state(true);
        } catch (\Throwable $e) {
            $expected = get_string('redirecterrordetected', 'error');
            if ($expected === $e->getMessage()) {
                $redirected = true;
            }
        }

        $this->assertFalse($redirected, 'The function cause a redirect, where none was expected.');
    }
}
