// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data93.g.dart';

@JsonSerializable()
class Data93 {
  const Data93({
    required this.id,
    required this.branchId,
    required this.name,
    required this.purpose,
    required this.connection,
    required this.address,
    required this.port,
    required this.paperWidth,
    required this.stationId,
    required this.deviceId,
    required this.isActive,
    required this.encoding,
    required this.printAsImage,
  });

  factory Data93.fromJson(Map<String, Object?> json) => _$Data93FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String name;
  final String purpose;
  final String connection;
  final String? address;
  final int? port;
  @JsonKey(name: 'paper_width')
  final int paperWidth;
  @JsonKey(name: 'station_id')
  final int? stationId;
  @JsonKey(name: 'device_id')
  final String? deviceId;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final String? encoding;
  @JsonKey(name: 'print_as_image')
  final bool printAsImage;

  Map<String, Object?> toJson() => _$Data93ToJson(this);
}
