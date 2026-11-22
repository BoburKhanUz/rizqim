// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events.dart';

part 'data224.g.dart';

@JsonSerializable()
class Data224 {
  const Data224({required this.events});

  factory Data224.fromJson(Map<String, Object?> json) =>
      _$Data224FromJson(json);

  final List<Events> events;

  Map<String, Object?> toJson() => _$Data224ToJson(this);
}
