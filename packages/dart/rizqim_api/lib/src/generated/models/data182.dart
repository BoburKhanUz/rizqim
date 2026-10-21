// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data182.g.dart';

@JsonSerializable()
class Data182 {
  const Data182({required this.storeId, required this.inviteLink});

  factory Data182.fromJson(Map<String, Object?> json) =>
      _$Data182FromJson(json);

  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data182ToJson(this);
}
