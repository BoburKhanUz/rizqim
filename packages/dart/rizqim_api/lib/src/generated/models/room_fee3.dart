// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'room_fee3.g.dart';

@JsonSerializable()
class RoomFee3 {
  const RoomFee3({this.name, this.mxik, this.packageCode, this.unit});

  factory RoomFee3.fromJson(Map<String, Object?> json) =>
      _$RoomFee3FromJson(json);

  final String? name;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String? unit;

  Map<String, Object?> toJson() => _$RoomFee3ToJson(this);
}
