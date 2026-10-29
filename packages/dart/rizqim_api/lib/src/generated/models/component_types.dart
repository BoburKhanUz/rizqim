// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'component_types.g.dart';

@JsonSerializable()
class ComponentTypes {
  const ComponentTypes({required this.value, required this.label});

  factory ComponentTypes.fromJson(Map<String, Object?> json) =>
      _$ComponentTypesFromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$ComponentTypesToJson(this);
}
