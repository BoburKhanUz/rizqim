// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_banquets_payment_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosBanquetsPaymentSettingsRequestBody
_$V1OwnerPosBanquetsPaymentSettingsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPosBanquetsPaymentSettingsRequestBody(
  scheduleTemplate: (json['schedule_template'] as List<dynamic>?)
      ?.map((e) => ScheduleTemplate3.fromJson(e as Map<String, dynamic>))
      .toList(),
  cancellationRules: (json['cancellation_rules'] as List<dynamic>?)
      ?.map((e) => CancellationRules3.fromJson(e as Map<String, dynamic>))
      .toList(),
  reminderDays: (json['reminder_days'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
  overdueReminders: json['overdue_reminders'] as bool?,
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerPosBanquetsPaymentSettingsRequestBodyToJson(
  V1OwnerPosBanquetsPaymentSettingsRequestBody instance,
) => <String, dynamic>{
  'schedule_template': instance.scheduleTemplate,
  'cancellation_rules': instance.cancellationRules,
  'reminder_days': instance.reminderDays,
  'overdue_reminders': instance.overdueReminders,
  'version': instance.version,
};
