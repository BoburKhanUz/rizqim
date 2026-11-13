// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data187.g.dart';

@JsonSerializable()
class Data187 {
  const Data187({
    required this.userId,
    required this.staffCode,
    required this.pinSetAt,
  });

  factory Data187.fromJson(Map<String, Object?> json) =>
      _$Data187FromJson(json);

  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'pin_set_at')
  final String? pinSetAt;

  Map<String, Object?> toJson() => _$Data187ToJson(this);
}
