// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_combos_id_preview_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerCombosIdPreviewRequestBody _$V1OwnerCombosIdPreviewRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerCombosIdPreviewRequestBody(
  channel: json['channel'] == null
      ? null
      : OrderChannel.fromJson(json['channel'] as String),
  picks: (json['picks'] as List<dynamic>?)
      ?.map((e) => Picks.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$V1OwnerCombosIdPreviewRequestBodyToJson(
  V1OwnerCombosIdPreviewRequestBody instance,
) => <String, dynamic>{'channel': instance.channel, 'picks': instance.picks};
