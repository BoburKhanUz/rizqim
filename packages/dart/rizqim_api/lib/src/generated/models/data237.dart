// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event2.dart';
import 'settlement2.dart';

part 'data237.g.dart';

@JsonSerializable()
class Data237 {
  const Data237({required this.event, required this.settlement});

  factory Data237.fromJson(Map<String, Object?> json) =>
      _$Data237FromJson(json);

  final Event2 event;
  final Settlement2 settlement;

  Map<String, Object?> toJson() => _$Data237ToJson(this);
}
