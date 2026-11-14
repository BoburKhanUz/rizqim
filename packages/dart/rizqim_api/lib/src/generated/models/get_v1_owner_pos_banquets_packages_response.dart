// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data140.dart';

part 'get_v1_owner_pos_banquets_packages_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosBanquetsPackagesResponse {
  const GetV1OwnerPosBanquetsPackagesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosBanquetsPackagesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerPosBanquetsPackagesResponseFromJson(json);

  final bool success;
  final String message;
  final Data140 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosBanquetsPackagesResponseToJson(this);
}
