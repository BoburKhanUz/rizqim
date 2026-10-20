// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'regions.g.dart';

@JsonSerializable()
class Regions {
  const Regions({required this.id, required this.name, required this.isActive});

  factory Regions.fromJson(Map<String, Object?> json) =>
      _$RegionsFromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'is_active')
  final bool isActive;

  Map<String, Object?> toJson() => _$RegionsToJson(this);
}
