// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'districts.g.dart';

@JsonSerializable()
class Districts {
  const Districts({
    required this.id,
    required this.regionId,
    required this.name,
    required this.isActive,
  });

  factory Districts.fromJson(Map<String, Object?> json) =>
      _$DistrictsFromJson(json);

  final int id;
  @JsonKey(name: 'region_id')
  final int regionId;
  final String name;
  @JsonKey(name: 'is_active')
  final bool isActive;

  Map<String, Object?> toJson() => _$DistrictsToJson(this);
}
