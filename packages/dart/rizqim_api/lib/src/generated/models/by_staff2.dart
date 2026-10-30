// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_staff2.g.dart';

@JsonSerializable()
class ByStaff2 {
  const ByStaff2({
    required this.staffId,
    required this.name,
    required this.role,
    required this.personal,
    required this.poolShare,
    required this.poolShareClosed,
    required this.total,
  });

  factory ByStaff2.fromJson(Map<String, Object?> json) =>
      _$ByStaff2FromJson(json);

  @JsonKey(name: 'staff_id')
  final int staffId;
  final String? name;
  final String? role;
  final String personal;
  @JsonKey(name: 'pool_share')
  final String poolShare;
  @JsonKey(name: 'pool_share_closed')
  final String poolShareClosed;
  final String total;

  Map<String, Object?> toJson() => _$ByStaff2ToJson(this);
}
