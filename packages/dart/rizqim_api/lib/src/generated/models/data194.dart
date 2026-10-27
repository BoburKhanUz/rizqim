// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data194.g.dart';

@JsonSerializable()
class Data194 {
  const Data194({required this.storeId, required this.inviteLink});

  factory Data194.fromJson(Map<String, Object?> json) =>
      _$Data194FromJson(json);

  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'invite_link')
  final String inviteLink;

  Map<String, Object?> toJson() => _$Data194ToJson(this);
}
