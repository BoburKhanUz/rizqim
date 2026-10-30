// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'products8.g.dart';

@JsonSerializable()
class Products8 {
  const Products8({required this.productId, required this.stationId});

  factory Products8.fromJson(Map<String, Object?> json) =>
      _$Products8FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'station_id')
  final int stationId;

  Map<String, Object?> toJson() => _$Products8ToJson(this);
}
