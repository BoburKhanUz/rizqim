// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'schedule_template.dart';
import 'cancellation_rules.dart';

part 'data146.g.dart';

@JsonSerializable()
class Data146 {
  const Data146({
    required this.scheduleTemplate,
    required this.cancellationRules,
    required this.reminderDays,
    required this.overdueReminders,
    required this.version,
  });

  factory Data146.fromJson(Map<String, Object?> json) =>
      _$Data146FromJson(json);

  @JsonKey(name: 'schedule_template')
  final List<ScheduleTemplate> scheduleTemplate;
  @JsonKey(name: 'cancellation_rules')
  final List<CancellationRules> cancellationRules;
  @JsonKey(name: 'reminder_days')
  final List<int> reminderDays;
  @JsonKey(name: 'overdue_reminders')
  final bool overdueReminders;
  final int version;

  Map<String, Object?> toJson() => _$Data146ToJson(this);
}
