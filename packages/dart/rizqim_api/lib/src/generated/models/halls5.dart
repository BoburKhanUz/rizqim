// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events2.dart';

part 'halls5.g.dart';

@JsonSerializable()
class Halls5 {
  const Halls5({
    required this.id,
    required this.name,
    required this.isActive,
    required this.events,
  });

  factory Halls5.fromJson(Map<String, Object?> json) => _$Halls5FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final List<Events2> events;

  Map<String, Object?> toJson() => _$Halls5ToJson(this);
}
