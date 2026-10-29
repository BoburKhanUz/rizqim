// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'products5.g.dart';

@JsonSerializable()
class Products5 {
  const Products5({required this.productId, required this.stationId});

  factory Products5.fromJson(Map<String, Object?> json) =>
      _$Products5FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'station_id')
  final int stationId;

  Map<String, Object?> toJson() => _$Products5ToJson(this);
}
