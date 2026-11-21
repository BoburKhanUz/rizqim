// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event6.dart';
import 'summary8.dart';
import 'rows10.dart';
import 'entries7.dart';
import 'cancellation4.dart';

part 'data237.g.dart';

@JsonSerializable()
class Data237 {
  const Data237({
    required this.event,
    required this.summary,
    required this.rows,
    required this.entries,
    required this.cancellation,
  });

  factory Data237.fromJson(Map<String, Object?> json) =>
      _$Data237FromJson(json);

  final Event6 event;
  final Summary8 summary;
  final List<Rows10> rows;
  final List<Entries7> entries;
  final Cancellation4? cancellation;

  Map<String, Object?> toJson() => _$Data237ToJson(this);
}
