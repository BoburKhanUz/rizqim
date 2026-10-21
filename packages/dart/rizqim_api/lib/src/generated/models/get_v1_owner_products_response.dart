// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data.dart';

part 'get_v1_owner_products_response.g.dart';

@JsonSerializable()
class GetV1OwnerProductsResponse {
  const GetV1OwnerProductsResponse({required this.success, required this.data});

  factory GetV1OwnerProductsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerProductsResponseFromJson(json);

  final bool success;
  final List<Data> data;

  Map<String, Object?> toJson() => _$GetV1OwnerProductsResponseToJson(this);
}
