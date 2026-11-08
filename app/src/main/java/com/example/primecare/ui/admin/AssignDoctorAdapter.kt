package com.example.primecare.ui.admin

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.AdapterView
import android.widget.ArrayAdapter
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.example.primecare.databinding.ItemAssignDoctorBinding
import com.example.primecare.model.AppointmentDto
import com.example.primecare.model.DoctorDto
import com.example.primecare.util.FormatUtils

class AssignDoctorAdapter(
    private val onAssign: (AppointmentDto, DoctorDto) -> Unit
) : ListAdapter<AppointmentDto, AssignDoctorAdapter.ViewHolder>(Diff) {

    /** Active doctors to choose from; set before submitting a list. */
    var doctors: List<DoctorDto> = emptyList()

    /** Remembers each row's chosen doctor while the list scrolls / rebinds. */
    private val selection = mutableMapOf<Int, Int>()

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ItemAssignDoctorBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        val item = getItem(position)
        val b = holder.binding
        b.patientNameText.text = item.patientFullName
        b.timeReasonText.text = "${FormatUtils.isoToDisplay(item.appointment_date)} · ${FormatUtils.formatTime(item.appointment_time)} · ${item.reason}"

        val names = doctors.map { d ->
            if (d.specialty.isNullOrBlank()) d.fullName else "${d.fullName} (${d.specialty})"
        }
        b.doctorSpinner.adapter =
            ArrayAdapter(b.root.context, android.R.layout.simple_spinner_dropdown_item, names)
        b.doctorSpinner.setSelection(selection[item.appointment_id] ?: 0, false)
        b.doctorSpinner.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: View?, pos: Int, id: Long) {
                selection[item.appointment_id] = pos
            }
            override fun onNothingSelected(parent: AdapterView<*>?) {}
        }

        b.assignButton.isEnabled = doctors.isNotEmpty()
        b.assignButton.setOnClickListener {
            val index = b.doctorSpinner.selectedItemPosition
            doctors.getOrNull(index)?.let { onAssign(item, it) }
        }
    }

    class ViewHolder(val binding: ItemAssignDoctorBinding) : RecyclerView.ViewHolder(binding.root)

    private object Diff : DiffUtil.ItemCallback<AppointmentDto>() {
        override fun areItemsTheSame(old: AppointmentDto, new: AppointmentDto) =
            old.appointment_id == new.appointment_id
        override fun areContentsTheSame(old: AppointmentDto, new: AppointmentDto) = old == new
    }
}
