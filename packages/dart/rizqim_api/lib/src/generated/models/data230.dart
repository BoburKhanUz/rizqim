// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event.dart';
import 'summary5.dart';
import 'rows5.dart';
import 'entries4.dart';
import 'cancellation.dart';

part 'data230.g.dart';

@JsonSerializable()
class Data230 {
  const Data230({
    required this.event,
    required this.summary,
    required this.rows,
    required this.entries,
    required this.cancellation,
  });

  factory Data230.fromJson(Map<String, Object?> json) =>
      _$Data230FromJson(json);

  final Event event;
  final Summary5 summary;
  final List<Rows5> rows;
  final List<Entries4> entries;
  final Cancellation? cancellation;

  Map<String, Object?> toJson() => _$Data230ToJson(this);
}
