// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data182.g.dart';

@JsonSerializable()
class Data182 {
  const Data182({required this.changed, required this.brandId});

  factory Data182.fromJson(Map<String, Object?> json) =>
      _$Data182FromJson(json);

  final String changed;
  @JsonKey(name: 'brand_id')
  final int? brandId;

  Map<String, Object?> toJson() => _$Data182ToJson(this);
}
