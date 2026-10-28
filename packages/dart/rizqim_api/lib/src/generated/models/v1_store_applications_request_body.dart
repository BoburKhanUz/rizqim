// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store_format.dart';
import 'source3.dart';

part 'v1_store_applications_request_body.g.dart';

@JsonSerializable()
class V1StoreApplicationsRequestBody {
  const V1StoreApplicationsRequestBody({
    required this.name,
    required this.format,
    required this.ownerName,
    required this.phone,
    this.regionId,
    this.city,
    this.tin,
    this.comment,
    this.source,
    this.website,
  });

  factory V1StoreApplicationsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1StoreApplicationsRequestBodyFromJson(json);

  final String name;
  final StoreFormat format;
  @JsonKey(name: 'region_id')
  final int? regionId;
  final String? city;
  @JsonKey(name: 'owner_name')
  final String ownerName;
  final String phone;
  final String? tin;
  final String? comment;
  final Source3? source;
  final String? website;

  Map<String, Object?> toJson() => _$V1StoreApplicationsRequestBodyToJson(this);
}
