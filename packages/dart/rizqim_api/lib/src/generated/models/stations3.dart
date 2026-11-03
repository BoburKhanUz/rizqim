// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'stations3.g.dart';

@JsonSerializable()
class Stations3 {
  const Stations3({
    required this.id,
    required this.name,
    required this.kind,
    required this.kindLabel,
    required this.isActive,
    required this.targetSeconds,
    required this.defaultTargetSeconds,
  });

  factory Stations3.fromJson(Map<String, Object?> json) =>
      _$Stations3FromJson(json);

  final int id;
  final String name;
  final String kind;
  @JsonKey(name: 'kind_label')
  final String kindLabel;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'target_seconds')
  final int? targetSeconds;
  @JsonKey(name: 'default_target_seconds')
  final int? defaultTargetSeconds;

  Map<String, Object?> toJson() => _$Stations3ToJson(this);
}
