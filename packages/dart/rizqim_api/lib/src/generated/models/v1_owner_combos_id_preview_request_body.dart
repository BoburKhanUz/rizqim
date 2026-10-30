// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order_channel.dart';
import 'picks.dart';

part 'v1_owner_combos_id_preview_request_body.g.dart';

@JsonSerializable()
class V1OwnerCombosIdPreviewRequestBody {
  const V1OwnerCombosIdPreviewRequestBody({this.channel, this.picks});

  factory V1OwnerCombosIdPreviewRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerCombosIdPreviewRequestBodyFromJson(json);

  final OrderChannel? channel;
  final List<Picks>? picks;

  Map<String, Object?> toJson() =>
      _$V1OwnerCombosIdPreviewRequestBodyToJson(this);
}
