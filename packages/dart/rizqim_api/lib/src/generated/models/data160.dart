// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data160.g.dart';

@JsonSerializable()
class Data160 {
  const Data160({
    required this.userId,
    required this.staffCode,
    required this.pinSetAt,
  });

  factory Data160.fromJson(Map<String, Object?> json) =>
      _$Data160FromJson(json);

  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'pin_set_at')
  final String? pinSetAt;

  Map<String, Object?> toJson() => _$Data160ToJson(this);
}
