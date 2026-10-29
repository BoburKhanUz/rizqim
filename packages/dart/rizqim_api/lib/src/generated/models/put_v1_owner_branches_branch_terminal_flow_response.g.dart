// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_branches_branch_terminal_flow_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerBranchesBranchTerminalFlowResponse
_$PutV1OwnerBranchesBranchTerminalFlowResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerBranchesBranchTerminalFlowResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data43.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerBranchesBranchTerminalFlowResponseToJson(
  PutV1OwnerBranchesBranchTerminalFlowResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
