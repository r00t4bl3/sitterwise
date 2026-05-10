"use client"

import * as React from "react"
import { format } from "date-fns"
import { Calendar as CalendarIcon, Clock } from "lucide-react"
import { parseAsLocal } from "@/lib/datetime"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

interface DateTimePickerProps {
    value?: string
    onChange?: (datetime: string) => void
    placeholder?: string
    error?: string
    startTime?: string  // For disabling invalid time options
}

export function DateTimePicker({
    value,
    onChange,
    placeholder = "Pick date and time",
    error,
    startTime}: DateTimePickerProps) {
    const [date, setDate] = React.useState<Date | undefined>(
        value ? parseAsLocal(value) ?? undefined : undefined
    )
    const [time, setTime] = React.useState(
        value ? format(parseAsLocal(value) as Date, "HH:mm") : "09:00"
    )

    const startDate = startTime ? parseAsLocal(startTime) : null

    const MIN_DURATION_MS = 4 * 60 * 60 * 1000

    const timeOptions = React.useMemo(() => {
    const options = []

    for (let i = 0; i < 96; i++) {
      const totalMins = i * 15
      const hours24 = Math.floor(totalMins / 60)
      const minutes = totalMins % 60
      const hours12 = hours24 === 0 ? 12 : hours24 > 12 ? hours24 - 12 : hours24
      const ampm = hours24 < 12 ? 'AM' : 'PM'

      const timeValue = `${String(hours24).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
      const label = `${hours12}:${String(minutes).padStart(2, '0')} ${ampm}`

      let disabled = false
      if (startDate && date) {
        const optionDate = new Date(date)
        optionDate.setHours(hours24, minutes, 0, 0)
        const diffMs = optionDate.getTime() - startDate.getTime()
        disabled = diffMs < MIN_DURATION_MS
      }

      options.push({
        value: timeValue,
        label: disabled ? `${label} (min 4h)` : label,
        disabled
      })
    }
    return options
  }, [startTime, date])
    const [open, setOpen] = React.useState(false)

    React.useEffect(() => {
        if (value) {
            const d = parseAsLocal(value)
            if (d) {
                setDate(d)
                setTime(format(d, "HH:mm"))
            }
        }
    }, [value])

    // Enforce minimum 4-hour duration from startTime
    React.useEffect(() => {
        if (!startDate || !value || !onChange) return

        const current = parseAsLocal(value)
        if (!current) return

        const diffMs = current.getTime() - startDate.getTime()
        const diffHours = diffMs / (1000 * 60 * 60)

        if (diffHours < 4) {
            const minDate = new Date(startDate.getTime() + 4 * 60 * 60 * 1000)
            const corrected = format(minDate, "yyyy-MM-dd'T'HH:mm")
            setDate(minDate)
            setTime(format(minDate, "HH:mm"))
            onChange(corrected)
        }
    }, [value, startTime])

    const handleDateSelect = (selectedDate: Date | undefined) => {
        setDate(selectedDate)
        if (selectedDate && onChange) {
            const hours24 = parseTimeTo24Hour(time)
            const minutes = parseTimeMinutes(time)
            const newDate = new Date(selectedDate)
            newDate.setHours(hours24, minutes, 0, 0)
            onChange(format(newDate, "yyyy-MM-dd'T'HH:mm"))
        }
        setOpen(false)
    }

    const parseTimeTo24Hour = (timeStr: string): number => {
        if (!timeStr.includes('AM') && !timeStr.includes('PM')) {
            const [h] = timeStr.split(':').map(Number)
            return h
        }
        const match = timeStr.match(/^(\d+):(\d+)\s*(AM|PM)$/i)
        if (!match) return 0
        let hours = parseInt(match[1], 10)
        const ampm = match[3].toUpperCase()
        if (ampm === 'AM' && hours === 12) return 0
        if (ampm === 'AM') return hours
        if (ampm === 'PM' && hours === 12) return 12
        return hours + 12
    }

    const parseTimeMinutes = (timeStr: string): number => {
        if (!timeStr.includes('AM') && !timeStr.includes('PM')) {
            const [, m] = timeStr.split(':').map(Number)
            return m || 0
        }
        const match = timeStr.match(/^(\d+):(\d+)\s*(AM|PM)$/i)
        return match ? parseInt(match[2], 10) : 0
    }

    return (
        <div className="flex gap-2">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        data-empty={!date}
                        className={`flex-1 justify-start text-left font-normal data-[empty=true]:text-muted-foreground ${
                            error ? "border-red-500" : ""
                        }`}
                    >
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {date ? format(date, "MMM d, yyyy") : <span>{placeholder}</span>}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={date}
                        onSelect={handleDateSelect}
                        defaultMonth={date}
                        disabled={startDate ? { before: startDate } : undefined}
                        initialFocus
                    />
                </PopoverContent>
            </Popover>
            <Select value={time} onValueChange={(newTime) => {
                setTime(newTime);
                if (date && onChange) {
                    const hours24 = parseTimeTo24Hour(newTime);
                    const minutes = parseTimeMinutes(newTime);
                    const newDate = new Date(date);
                    newDate.setHours(hours24, minutes, 0, 0);
                    onChange(format(newDate, "yyyy-MM-dd'T'HH:mm"));
                }
            }}>
                <SelectTrigger
                    className={`w-28 ${error ? "border-red-500" : ""}`}
                >
                    <SelectValue placeholder="Time" />
                </SelectTrigger>
                <SelectContent>
                    {timeOptions.map((t) => (
                        <SelectItem
                            key={t.value}
                            value={t.value}
                            disabled={t.disabled}
                        >
                            {t.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    )
}
