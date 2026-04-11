"use client"

import * as React from "react"
import { format } from "date-fns"
import { Calendar as CalendarIcon, Clock } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover"

interface DateTimePickerProps {
    value?: string
    onChange?: (datetime: string) => void
    placeholder?: string
    error?: string
}

export function DateTimePicker({
    value,
    onChange,
    placeholder = "Pick date and time",
    error,
}: DateTimePickerProps) {
    const [date, setDate] = React.useState<Date | undefined>(
        value ? new Date(value) : undefined
    )
    const [time, setTime] = React.useState(
        value ? format(new Date(value), "HH:mm") : "09:00"
    )

    const timeOptions = Array.from({ length: 96 }, (_, i) => {
        const totalMins = i * 15
        const hh = String(Math.floor(totalMins / 60)).padStart(2, "0")
        const mm = String(totalMins % 60).padStart(2, "0")
        return `${hh}:${mm}`
    })
    const [open, setOpen] = React.useState(false)

    React.useEffect(() => {
        if (value) {
            const d = new Date(value)
            setDate(d)
            setTime(format(d, "HH:mm"))
        }
    }, [value])

    const handleDateSelect = (selectedDate: Date | undefined) => {
        setDate(selectedDate)
        if (selectedDate && onChange) {
            const [hours, minutes] = time.split(":").map(Number)
            const newDate = new Date(selectedDate)
            newDate.setHours(hours, minutes, 0, 0)
            onChange(format(newDate, "yyyy-MM-dd'T'HH:mm"))
        }
        setOpen(false)
    }

    const handleTimeChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newTime = e.target.value
        setTime(newTime)
        if (date && onChange) {
            const [hours, minutes] = newTime.split(":").map(Number)
            const newDate = new Date(date)
            newDate.setHours(hours, minutes, 0, 0)
            onChange(format(newDate, "yyyy-MM-dd'T'HH:mm"))
        }
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
                        initialFocus
                    />
                </PopoverContent>
            </Popover>
            <select
                value={time}
                onChange={handleTimeChange}
                className={`h-10 w-28 rounded-[3px] border bg-background px-3 text-sm ${
                    error ? "border-red-500" : "border-input"
                }`}
            >
                {timeOptions.map((t) => (
                    <option key={t} value={t}>
                        {t}
                    </option>
                ))}
            </select>
        </div>
    )
}
