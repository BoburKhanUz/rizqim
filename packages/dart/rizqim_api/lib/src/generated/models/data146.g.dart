// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data146.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data146 _$Data146FromJson(Map<String, dynamic> json) => Data146(
  scheduleTemplate: (json['schedule_template'] as List<dynamic>)
      .map((e) => ScheduleTemplate.fromJson(e as Map<String, dynamic>))
      .toList(),
  cancellationRules: (json['cancellation_rules'] as List<dynamic>)
      .map((e) => CancellationRules.fromJson(e as Map<String, dynamic>))
      .toList(),
  reminderDays: (json['reminder_days'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  overdueReminders: json['overdue_reminders'] as bool,
  version: (json['version'] as num).toInt(),
);

Map<String, dynamic> _$Data146ToJson(Data146 instance) => <String, dynamic>{
  'schedule_template': instance.scheduleTemplate,
  'cancellation_rules': instance.cancellationRules,
  'reminder_days': instance.reminderDays,
  'overdue_reminders': instance.overdueReminders,
  'version': instance.version,
};
