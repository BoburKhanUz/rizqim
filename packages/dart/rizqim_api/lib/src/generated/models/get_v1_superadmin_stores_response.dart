// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data178.dart';

part 'get_v1_superadmin_stores_response.g.dart';

@JsonSerializable()
class GetV1SuperadminStoresResponse {
  const GetV1SuperadminStoresResponse({
    required this.success,
    required this.data,
  });

  factory GetV1SuperadminStoresResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1SuperadminStoresResponseFromJson(json);

  final bool success;
  final List<Data178> data;

  Map<String, Object?> toJson() => _$GetV1SuperadminStoresResponseToJson(this);
}
