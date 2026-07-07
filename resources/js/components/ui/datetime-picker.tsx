"use client"

import * as React from "react"
import { format } from "date-fns"
import { Calendar as CalendarIcon, Clock } from "lucide-react"
import { formatUtcStringFromPt } from "@/lib/datetime"

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
    minDate?: Date  // Earliest selectable date
}

export function DateTimePicker({
    value,
    onChange,
    placeholder = "Pick date and time",
    error,
    startTime,
    minDate}: DateTimePickerProps) {
    const parsePT = React.useCallback((dateStr: string | null | undefined): Date | null => {
        if (!dateStr) return null
        const s = dateStr.replace(/\.(\d{3})\d*Z$/, '.$1Z')
        const d = new Date(s)
        if (isNaN(d.getTime())) return null
        const p = new Intl.DateTimeFormat('en-US', {
            timeZone: 'America/Los_Angeles',
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hour12: false,
        }).formatToParts(d)
        const g = (t: string) => parseInt(p.find(x => x.type === t)!.value, 10)
        return new Date(g('year'), g('month') - 1, g('day'), g('hour'), g('minute'))
    }, [])

    const [date, setDate] = React.useState<Date | undefined>(() => {
        const parsed = value ? parsePT(value) : null
        return parsed && !isNaN(parsed.getTime()) ? parsed : undefined
    })
    const [time, setTime] = React.useState(() => {
        const parsed = value ? parsePT(value) : null
        return parsed && !isNaN(parsed.getTime()) ? format(parsed, "HH:mm") : "09:00"
    })

    const startDate = startTime ? parsePT(startTime) : null
    const minDateConstraint = startDate ? { before: startDate } : minDate ? { before: minDate } : undefined

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

    // Preserve an off-grid time (e.g. a 2:37 PM checkout) so the Select still
    // displays it instead of falling back to the blank "Time" placeholder.
    if (time && !options.some((o) => o.value === time)) {
      const [h, m] = time.split(':').map(Number)
      if (!isNaN(h) && !isNaN(m)) {
        const hours12 = h === 0 ? 12 : h > 12 ? h - 12 : h
        const ampm = h < 12 ? 'AM' : 'PM'
        const label = `${hours12}:${String(m).padStart(2, '0')} ${ampm}`

        let disabled = false
        if (startDate && date) {
          const optionDate = new Date(date)
          optionDate.setHours(h, m, 0, 0)
          disabled = optionDate.getTime() - startDate.getTime() < MIN_DURATION_MS
        }

        options.push({ value: time, label: disabled ? `${label} (min 4h)` : label, disabled })
        options.sort((a, b) => a.value.localeCompare(b.value))
      }
    }

    return options
  }, [startTime, date, time])
    const [open, setOpen] = React.useState(false)

    React.useEffect(() => {
        if (value) {
            const d = parsePT(value)
            if (d && !isNaN(d.getTime())) {
                setDate(d)
                setTime(format(d, "HH:mm"))
            }
        }
    }, [value])

    // Enforce minimum 4-hour duration from startTime
    React.useEffect(() => {
        if (!startDate || isNaN(startDate.getTime()) || !value || !onChange) return

        const current = parsePT(value)
        if (!current || isNaN(current.getTime())) return

        const diffMs = current.getTime() - startDate.getTime()
        const diffHours = diffMs / (1000 * 60 * 60)

        if (diffHours < 4) {
            const minDate = new Date(startDate.getTime() + 4 * 60 * 60 * 1000)
            setDate(minDate)
            setTime(format(minDate, "HH:mm"))
            onChange(formatUtcStringFromPt(minDate))
        }
    }, [value, startTime])

    const handleDateSelect = (selectedDate: Date | undefined) => {
        setDate(selectedDate)
        if (selectedDate && onChange) {
            const hours24 = parseTimeTo24Hour(time)
            const minutes = parseTimeMinutes(time)
            const newDate = new Date(selectedDate)
            newDate.setHours(hours24, minutes, 0, 0)
            onChange(formatUtcStringFromPt(newDate))
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
                        disabled={minDateConstraint}
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
                    onChange(formatUtcStringFromPt(newDate));
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
