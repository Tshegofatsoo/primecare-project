package com.example.primecare.util

import android.graphics.drawable.GradientDrawable
import android.widget.TextView
import com.example.primecare.R
import com.example.primecare.model.AppointmentDto
import java.time.LocalDate
import java.time.LocalTime
import java.time.format.DateTimeFormatter
import java.time.format.DateTimeParseException

/**
 * Shared formatting helpers used across every screen that displays
 * appointments/queue data — kept in one place so the time format and
 * status colors never drift out of sync between screens.
 */
object FormatUtils {

    private val displayDate = DateTimeFormatter.ofPattern("dd/MM/yyyy")
    private val prettyDate = DateTimeFormatter.ofPattern("EEE, d MMM")

    /** MySQL TIME "09:30:00" (or "09:30") -> "9:30 AM" */
    fun formatTime(rawTime: String): String {
        for (pattern in listOf("HH:mm:ss", "HH:mm")) {
            try {
                val time = LocalTime.parse(rawTime, DateTimeFormatter.ofPattern(pattern))
                return time.format(DateTimeFormatter.ofPattern("h:mm a"))
            } catch (e: DateTimeParseException) {
                // try the next pattern
            }
        }
        return rawTime
    }

    /** Date-picker result -> "05/03/2026" (month is 0-based, like DatePickerDialog). */
    fun formatDisplayDate(year: Int, month0: Int, day: Int): String =
        LocalDate.of(year, month0 + 1, day).format(displayDate)

    /** "05/03/2026" -> "2026-03-05", or null if it isn't a valid date. */
    fun displayToIso(display: String): String? = try {
        LocalDate.parse(display.trim(), displayDate).toString()
    } catch (e: DateTimeParseException) {
        null
    }

    /** "2026-03-05" -> "05/03/2026"; returns the input unchanged if it can't be parsed. */
    fun isoToDisplay(iso: String): String = try {
        LocalDate.parse(iso).format(displayDate)
    } catch (e: DateTimeParseException) {
        iso
    }

    /** "2026-11-09" -> "Mon, 9 Nov"; returns the input unchanged if it can't be parsed. */
    fun isoToPretty(iso: String): String = try {
        LocalDate.parse(iso).format(prettyDate)
    } catch (e: DateTimeParseException) {
        iso
    }

    fun statusLabel(status: String, inConsultation: Boolean): String = when {
        status == "CheckedIn" && inConsultation -> "In consultation"
        status == "CheckedIn" -> "Checked in"
        else -> status
    }

    /**
     * Same status -> color mapping as the patient website's pills
     * (blue/amber/green/red), plus solid blue for "In consultation".
     */
    fun applyStatusPill(pill: TextView, appointment: AppointmentDto) {
        val context = pill.context
        val inConsultation = appointment.in_consultation
        val (bgColor, textColor) = when {
            appointment.status == "CheckedIn" && inConsultation -> R.color.pc_blue to R.color.pc_white
            appointment.status == "Booked" -> R.color.pc_blue_tint to R.color.pc_blue_dark
            appointment.status == "CheckedIn" -> R.color.pc_amber_bg to R.color.pc_amber
            appointment.status == "Completed" -> R.color.pc_green_bg to R.color.pc_green
            appointment.status == "Cancelled" -> R.color.pc_red_bg to R.color.pc_red
            else -> R.color.pc_grey_bg to R.color.pc_text_grey
        }

        val background = pill.background.mutate() as? GradientDrawable
        background?.setColor(context.getColor(bgColor))
        pill.setTextColor(context.getColor(textColor))
        pill.text = statusLabel(appointment.status, inConsultation)
    }

    fun doctorOrUnassigned(doctorFullName: String?): String =
        doctorFullName ?: "To be assigned"
}
