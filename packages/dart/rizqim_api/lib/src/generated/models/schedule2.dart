// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event8.dart';
import 'summary10.dart';
import 'rows12.dart';
import 'entries9.dart';
import 'cancellation6.dart';

part 'schedule2.g.dart';

@JsonSerializable()
class Schedule2 {
  const Schedule2({
    required this.event,
    required this.summary,
    required this.rows,
    required this.entries,
    required this.cancellation,
  });

  factory Schedule2.fromJson(Map<String, Object?> json) =>
      _$Schedule2FromJson(json);

  final Event8 event;
  final Summary10 summary;
  final List<Rows12> rows;
  final List<Entries9> entries;
  final Cancellation6? cancellation;

  Map<String, Object?> toJson() => _$Schedule2ToJson(this);
}
