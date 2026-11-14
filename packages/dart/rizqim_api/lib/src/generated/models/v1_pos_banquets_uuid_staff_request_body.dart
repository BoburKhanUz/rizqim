// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_banquets_uuid_staff_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidStaffRequestBody {
  const V1PosBanquetsUuidStaffRequestBody({required this.userIds});

  factory V1PosBanquetsUuidStaffRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidStaffRequestBodyFromJson(json);

  @JsonKey(name: 'user_ids')
  final List<int> userIds;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidStaffRequestBodyToJson(this);
}
