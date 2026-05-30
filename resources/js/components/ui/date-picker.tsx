"use client"

import * as React from "react"
import { format } from "date-fns"
import { Calendar as CalendarIcon } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

interface DatePickerProps {
  value?: string
  onChange?: (date: string) => void
  placeholder?: string
  name?: string
  fromYear?: number
  toYear?: number
  disabled?: { before: Date } | { after: Date }
}

export function DatePicker({
  value,
  onChange,
  placeholder = "Pick a date",
  name,
  fromYear,
  toYear,
  disabled,
}: DatePickerProps) {
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

  const startMonth = fromYear ? new Date(fromYear, 0) : undefined
  const endMonth = toYear ? new Date(toYear, 11) : undefined
  const [date, setDate] = React.useState<Date | undefined>(
    value ? parsePT(value) ?? undefined : undefined
  )
  const [open, setOpen] = React.useState(false)

  React.useEffect(() => {
    if (value) {
      setDate(parsePT(value) ?? undefined)
    }
  }, [value])

  const handleSelect = (selectedDate: Date | undefined) => {
    setDate(selectedDate)
    if (selectedDate && onChange) {
      onChange(format(selectedDate, "yyyy-MM-dd"))
    }
    setOpen(false)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          data-empty={!date}
          className="w-full justify-start text-left font-normal data-[empty=true]:text-muted-foreground"
        >
          <CalendarIcon className="mr-2 h-4 w-4" />
          {date ? format(date, "PPP") : <span>{placeholder}</span>}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={date}
          onSelect={handleSelect}
          defaultMonth={date}
          captionLayout="dropdown"
          startMonth={startMonth}
          endMonth={endMonth}
          disabled={disabled}
        />
      </PopoverContent>
      {name && (
        <input type="hidden" name={name} value={value || ""} />
      )}
    </Popover>
  )
}