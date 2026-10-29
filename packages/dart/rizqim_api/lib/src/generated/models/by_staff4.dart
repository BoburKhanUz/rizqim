// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_staff4.g.dart';

@JsonSerializable()
class ByStaff4 {
  const ByStaff4({
    required this.staffId,
    required this.name,
    required this.amount,
  });

  factory ByStaff4.fromJson(Map<String, Object?> json) =>
      _$ByStaff4FromJson(json);

  @JsonKey(name: 'staff_id')
  final int staffId;
  final String? name;
  final String amount;

  Map<String, Object?> toJson() => _$ByStaff4ToJson(this);
}
