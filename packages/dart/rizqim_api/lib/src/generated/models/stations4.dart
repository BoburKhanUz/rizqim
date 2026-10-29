// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'stations4.g.dart';

@JsonSerializable()
class Stations4 {
  const Stations4({
    required this.name,
    required this.kind,
    required this.kindLabel,
    required this.exists,
  });

  factory Stations4.fromJson(Map<String, Object?> json) =>
      _$Stations4FromJson(json);

  final String name;
  final String kind;
  @JsonKey(name: 'kind_label')
  final String kindLabel;
  final bool exists;

  Map<String, Object?> toJson() => _$Stations4ToJson(this);
}
