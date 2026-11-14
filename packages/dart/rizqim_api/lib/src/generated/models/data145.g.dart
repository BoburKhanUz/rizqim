// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data145.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data145 _$Data145FromJson(Map<String, dynamic> json) => Data145(
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

Map<String, dynamic> _$Data145ToJson(Data145 instance) => <String, dynamic>{
  'schedule_template': instance.scheduleTemplate,
  'cancellation_rules': instance.cancellationRules,
  'reminder_days': instance.reminderDays,
  'overdue_reminders': instance.overdueReminders,
  'version': instance.version,
};
