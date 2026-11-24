// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data148.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data148 _$Data148FromJson(Map<String, dynamic> json) => Data148(
  scheduleTemplate: (json['schedule_template'] as List<dynamic>)
      .map((e) => ScheduleTemplate2.fromJson(e as Map<String, dynamic>))
      .toList(),
  cancellationRules: (json['cancellation_rules'] as List<dynamic>)
      .map((e) => CancellationRules2.fromJson(e as Map<String, dynamic>))
      .toList(),
  reminderDays: (json['reminder_days'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  overdueReminders: json['overdue_reminders'] as bool,
  version: (json['version'] as num).toInt(),
);

Map<String, dynamic> _$Data148ToJson(Data148 instance) => <String, dynamic>{
  'schedule_template': instance.scheduleTemplate,
  'cancellation_rules': instance.cancellationRules,
  'reminder_days': instance.reminderDays,
  'overdue_reminders': instance.overdueReminders,
  'version': instance.version,
};
