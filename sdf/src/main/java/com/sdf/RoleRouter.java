package com.sdf;

import com.sdf.screens.LandingScreen;
import com.sdf.screens.admin.AdminDashboardScreen;
import com.sdf.screens.lecturer.LecturerDashboardScreen;
import com.sdf.screens.student.StudentDashboardScreen;
import com.sdf.session.Session;

/**
 * After login/register, the API tells us the user's roles
 * (Session.get().getRoles()). This picks the right landing screen —
 * mirrors the role-based routing the web app does after Livewire auth.
 */
public final class RoleRouter {

    private RoleRouter() {}

    public static void navigateToDashboard() {
        Session s = Session.get();
        if (s.isAdmin()) {
            Router.get().navigate(AdminDashboardScreen::build);
        } else if (s.isLecturer()) {
            Router.get().navigate(LecturerDashboardScreen::build);
        } else {
            Router.get().navigate(StudentDashboardScreen::build);
        }
    }

    /** Clears the session and sends the user back to the landing/login flow. */
    public static void logout() {
        com.sdf.api.AuthService.logout();
        Router.get().navigate(LandingScreen::build);
    }
}
