// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events.dart';

part 'data223.g.dart';

@JsonSerializable()
class Data223 {
  const Data223({required this.events});

  factory Data223.fromJson(Map<String, Object?> json) =>
      _$Data223FromJson(json);

  final List<Events> events;

  Map<String, Object?> toJson() => _$Data223ToJson(this);
}
