// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data50.g.dart';

@JsonSerializable()
class Data50 {
  const Data50({
    required this.userId,
    required this.staffCode,
    required this.pinSetAt,
  });

  factory Data50.fromJson(Map<String, Object?> json) => _$Data50FromJson(json);

  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'pin_set_at')
  final String? pinSetAt;

  Map<String, Object?> toJson() => _$Data50ToJson(this);
}
