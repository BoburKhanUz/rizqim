// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'schedule_template3.dart';
import 'cancellation_rules3.dart';

part 'v1_owner_pos_banquets_payment_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosBanquetsPaymentSettingsRequestBody {
  const V1OwnerPosBanquetsPaymentSettingsRequestBody({
    this.scheduleTemplate,
    this.cancellationRules,
    this.reminderDays,
    this.overdueReminders,
    this.version,
  });

  factory V1OwnerPosBanquetsPaymentSettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosBanquetsPaymentSettingsRequestBodyFromJson(json);

  @JsonKey(name: 'schedule_template')
  final List<ScheduleTemplate3>? scheduleTemplate;
  @JsonKey(name: 'cancellation_rules')
  final List<CancellationRules3>? cancellationRules;
  @JsonKey(name: 'reminder_days')
  final List<int>? reminderDays;
  @JsonKey(name: 'overdue_reminders')
  final bool? overdueReminders;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosBanquetsPaymentSettingsRequestBodyToJson(this);
}
