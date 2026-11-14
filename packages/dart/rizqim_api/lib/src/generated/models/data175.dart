// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data175.g.dart';

@JsonSerializable()
class Data175 {
  const Data175({required this.changed, required this.brandId});

  factory Data175.fromJson(Map<String, Object?> json) =>
      _$Data175FromJson(json);

  final String changed;
  @JsonKey(name: 'brand_id')
  final int? brandId;

  Map<String, Object?> toJson() => _$Data175ToJson(this);
}
