// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data133.g.dart';

@JsonSerializable()
class Data133 {
  const Data133({
    required this.userId,
    required this.staffCode,
    required this.pinSetAt,
  });

  factory Data133.fromJson(Map<String, Object?> json) =>
      _$Data133FromJson(json);

  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'pin_set_at')
  final String? pinSetAt;

  Map<String, Object?> toJson() => _$Data133ToJson(this);
}
