// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events.dart';

part 'data218.g.dart';

@JsonSerializable()
class Data218 {
  const Data218({required this.events});

  factory Data218.fromJson(Map<String, Object?> json) =>
      _$Data218FromJson(json);

  final List<Events> events;

  Map<String, Object?> toJson() => _$Data218ToJson(this);
}
