// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data181.g.dart';

@JsonSerializable()
class Data181 {
  const Data181({required this.changed, required this.brandId});

  factory Data181.fromJson(Map<String, Object?> json) =>
      _$Data181FromJson(json);

  final String changed;
  @JsonKey(name: 'brand_id')
  final int? brandId;

  Map<String, Object?> toJson() => _$Data181ToJson(this);
}
