// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event.dart';
import 'summary5.dart';
import 'rows5.dart';
import 'entries4.dart';
import 'cancellation.dart';

part 'data227.g.dart';

@JsonSerializable()
class Data227 {
  const Data227({
    required this.event,
    required this.summary,
    required this.rows,
    required this.entries,
    required this.cancellation,
  });

  factory Data227.fromJson(Map<String, Object?> json) =>
      _$Data227FromJson(json);

  final Event event;
  final Summary5 summary;
  final List<Rows5> rows;
  final List<Entries4> entries;
  final Cancellation? cancellation;

  Map<String, Object?> toJson() => _$Data227ToJson(this);
}
