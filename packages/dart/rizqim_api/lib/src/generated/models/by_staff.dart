// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_staff.g.dart';

@JsonSerializable()
class ByStaff {
  const ByStaff({
    required this.staffId,
    required this.name,
    required this.amount,
  });

  factory ByStaff.fromJson(Map<String, Object?> json) =>
      _$ByStaffFromJson(json);

  @JsonKey(name: 'staff_id')
  final int staffId;
  final String? name;
  final String amount;

  Map<String, Object?> toJson() => _$ByStaffToJson(this);
}
