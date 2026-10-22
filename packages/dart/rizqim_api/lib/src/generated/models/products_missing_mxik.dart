// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'products_missing_mxik.g.dart';

@JsonSerializable()
class ProductsMissingMxik {
  const ProductsMissingMxik({
    required this.id,
    required this.uuid,
    required this.name,
    required this.kind,
    required this.status,
    required this.categoryId,
  });

  factory ProductsMissingMxik.fromJson(Map<String, Object?> json) =>
      _$ProductsMissingMxikFromJson(json);

  final int id;
  final String uuid;
  final String name;
  final String kind;
  final String status;
  @JsonKey(name: 'category_id')
  final int categoryId;

  Map<String, Object?> toJson() => _$ProductsMissingMxikToJson(this);
}
