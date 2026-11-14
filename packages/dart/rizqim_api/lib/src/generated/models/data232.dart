// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event4.dart';
import 'summary6.dart';
import 'rows7.dart';
import 'entries5.dart';
import 'cancellation2.dart';

part 'data232.g.dart';

@JsonSerializable()
class Data232 {
  const Data232({
    required this.event,
    required this.summary,
    required this.rows,
    required this.entries,
    required this.cancellation,
  });

  factory Data232.fromJson(Map<String, Object?> json) =>
      _$Data232FromJson(json);

  final Event4 event;
  final Summary6 summary;
  final List<Rows7> rows;
  final List<Entries5> entries;
  final Cancellation2? cancellation;

  Map<String, Object?> toJson() => _$Data232ToJson(this);
}
