// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event5.dart';
import 'summary7.dart';
import 'rows8.dart';
import 'entries6.dart';
import 'cancellation3.dart';

part 'data233.g.dart';

@JsonSerializable()
class Data233 {
  const Data233({
    required this.event,
    required this.summary,
    required this.rows,
    required this.entries,
    required this.cancellation,
  });

  factory Data233.fromJson(Map<String, Object?> json) =>
      _$Data233FromJson(json);

  final Event5 event;
  final Summary7 summary;
  final List<Rows8> rows;
  final List<Entries6> entries;
  final Cancellation3? cancellation;

  Map<String, Object?> toJson() => _$Data233ToJson(this);
}
