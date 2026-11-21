// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events.dart';

part 'data221.g.dart';

@JsonSerializable()
class Data221 {
  const Data221({required this.events});

  factory Data221.fromJson(Map<String, Object?> json) =>
      _$Data221FromJson(json);

  final List<Events> events;

  Map<String, Object?> toJson() => _$Data221ToJson(this);
}
