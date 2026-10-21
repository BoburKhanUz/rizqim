// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data179.g.dart';

@JsonSerializable()
class Data179 {
  const Data179({required this.storeId, required this.inviteLink});

  factory Data179.fromJson(Map<String, Object?> json) =>
      _$Data179FromJson(json);

  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data179ToJson(this);
}
