// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event7.dart';
import 'summary9.dart';
import 'rows11.dart';
import 'entries8.dart';
import 'cancellation5.dart';

part 'schedule.g.dart';

@JsonSerializable()
class Schedule {
  const Schedule({
    required this.event,
    required this.summary,
    required this.rows,
    required this.entries,
    required this.cancellation,
  });

  factory Schedule.fromJson(Map<String, Object?> json) =>
      _$ScheduleFromJson(json);

  final Event7 event;
  final Summary9 summary;
  final List<Rows11> rows;
  final List<Entries8> entries;
  final Cancellation5? cancellation;

  Map<String, Object?> toJson() => _$ScheduleToJson(this);
}
