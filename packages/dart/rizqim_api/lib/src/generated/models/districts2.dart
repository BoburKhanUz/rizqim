// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'districts2.g.dart';

@JsonSerializable()
class Districts2 {
  const Districts2({
    required this.id,
    required this.regionId,
    required this.name,
  });

  factory Districts2.fromJson(Map<String, Object?> json) =>
      _$Districts2FromJson(json);

  final int id;
  @JsonKey(name: 'region_id')
  final int regionId;
  final String name;

  Map<String, Object?> toJson() => _$Districts2ToJson(this);
}
