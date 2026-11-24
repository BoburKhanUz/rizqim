// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data218.g.dart';

@JsonSerializable()
class Data218 {
  const Data218({
    required this.userId,
    required this.staffCode,
    required this.pinSetAt,
  });

  factory Data218.fromJson(Map<String, Object?> json) =>
      _$Data218FromJson(json);

  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'pin_set_at')
  final String? pinSetAt;

  Map<String, Object?> toJson() => _$Data218ToJson(this);
}
