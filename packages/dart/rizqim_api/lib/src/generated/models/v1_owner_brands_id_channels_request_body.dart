// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channels3.dart';

part 'v1_owner_brands_id_channels_request_body.g.dart';

@JsonSerializable()
class V1OwnerBrandsIdChannelsRequestBody {
  const V1OwnerBrandsIdChannelsRequestBody({required this.channels});

  factory V1OwnerBrandsIdChannelsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBrandsIdChannelsRequestBodyFromJson(json);

  final List<Channels3> channels;

  Map<String, Object?> toJson() =>
      _$V1OwnerBrandsIdChannelsRequestBodyToJson(this);
}
