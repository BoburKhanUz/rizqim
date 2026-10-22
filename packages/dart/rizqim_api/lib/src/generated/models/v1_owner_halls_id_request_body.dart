// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_halls_id_request_body.g.dart';

@JsonSerializable()
class V1OwnerHallsIdRequestBody {
  const V1OwnerHallsIdRequestBody({this.name, this.sortOrder, this.isActive});

  factory V1OwnerHallsIdRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerHallsIdRequestBodyFromJson(json);

  final String? name;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;
  @JsonKey(name: 'is_active')
  final bool? isActive;

  Map<String, Object?> toJson() => _$V1OwnerHallsIdRequestBodyToJson(this);
}
