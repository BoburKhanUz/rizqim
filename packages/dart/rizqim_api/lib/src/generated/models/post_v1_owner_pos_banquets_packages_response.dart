// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data141.dart';

part 'post_v1_owner_pos_banquets_packages_response.g.dart';

@JsonSerializable()
class PostV1OwnerPosBanquetsPackagesResponse {
  const PostV1OwnerPosBanquetsPackagesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPosBanquetsPackagesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerPosBanquetsPackagesResponseFromJson(json);

  final bool success;
  final String message;
  final Data141 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerPosBanquetsPackagesResponseToJson(this);
}
