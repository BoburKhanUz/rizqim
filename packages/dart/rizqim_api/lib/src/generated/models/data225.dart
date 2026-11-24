// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events2.dart';

part 'data225.g.dart';

@JsonSerializable()
class Data225 {
  const Data225({required this.events});

  factory Data225.fromJson(Map<String, Object?> json) =>
      _$Data225FromJson(json);

  final List<Events2> events;

  Map<String, Object?> toJson() => _$Data225ToJson(this);
}
