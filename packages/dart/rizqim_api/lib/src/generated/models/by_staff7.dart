// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_staff7.g.dart';

@JsonSerializable()
class ByStaff7 {
  const ByStaff7({
    required this.staffId,
    required this.name,
    required this.amount,
  });

  factory ByStaff7.fromJson(Map<String, Object?> json) =>
      _$ByStaff7FromJson(json);

  @JsonKey(name: 'staff_id')
  final int staffId;
  final String? name;
  final String amount;

  Map<String, Object?> toJson() => _$ByStaff7ToJson(this);
}
