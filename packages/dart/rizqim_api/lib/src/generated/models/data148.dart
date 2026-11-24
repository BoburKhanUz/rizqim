// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'schedule_template2.dart';
import 'cancellation_rules2.dart';

part 'data148.g.dart';

@JsonSerializable()
class Data148 {
  const Data148({
    required this.scheduleTemplate,
    required this.cancellationRules,
    required this.reminderDays,
    required this.overdueReminders,
    required this.version,
  });

  factory Data148.fromJson(Map<String, Object?> json) =>
      _$Data148FromJson(json);

  @JsonKey(name: 'schedule_template')
  final List<ScheduleTemplate2> scheduleTemplate;
  @JsonKey(name: 'cancellation_rules')
  final List<CancellationRules2> cancellationRules;
  @JsonKey(name: 'reminder_days')
  final List<int> reminderDays;
  @JsonKey(name: 'overdue_reminders')
  final bool overdueReminders;
  final int version;

  Map<String, Object?> toJson() => _$Data148ToJson(this);
}
